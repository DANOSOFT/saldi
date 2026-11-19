<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebNormalize.php --- ver 5.1.1 --- 2026-11-19 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
// 20261006 CDX/LH Created Enable Banking ebNormalize backend service.
// 20261007 CDX/LH Decoded bank HTML entities before normalization and hashing.
// 20261007 CDX/LH Reviewed nonzero third decimals and applied explicit accounting date overrides.
// 20261007 CDX/LH Replaced non-BMP bank text for mysqli before normalization and hashing.
// 20261119 CL/LH The payee name shows once (Nordea repeats it in the payment text), the reference shown under the line is not repeated in the text, and the bank's balance after the line is kept for the day order.

require_once __DIR__ . '/ebDb.php';

/** @return string Collapsed Unicode whitespace and a UTF-8-safe character limit. */
function ebText(string $text, int $limit): string
{
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, $limit, 'UTF-8');
}

/**
 * @param bool $mysql Whether the tenant uses mysqli's utf8mb3 connection encoding.
 * @return string Text representable by the tenant backend, before identity hashes are computed.
 */
function ebBackendText(string $text, bool $mysql): string
{
    return $mysql ? (preg_replace('/[\x{10000}-\x{10FFFF}]/u', '�', $text) ?? '') : $text;
}

/**
 * @param array<string,mixed> $raw
 * @return array{status:string, entry_reference:?string, booking_date:?string, value_date:?string, transaction_date:?string,
 * amount:string, currency:string, description:string, counterparty:?string, bank_reference:?string, valid_amount:bool}
 */
function ebNormalizeTransaction(array $raw): array
{
    $mysql = ebMysql();
    array_walk_recursive($raw, static function (&$value) use ($mysql): void {
        if (is_string($value)) {
            $value = ebBackendText(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $mysql);
        }
    });
    $amountResource = is_array($raw['transaction_amount'] ?? null) ? $raw['transaction_amount'] : [];
    $input = $amountResource['amount'] ?? '';
    // JSON numeric amounts cannot preserve arbitrary decimal precision.
    $amount = is_string($input) ? trim($input) : '';
    $indicator = $raw['credit_debit_indicator'] ?? '';
    $negative = str_starts_with($amount, '-') && $indicator === '';
    if (str_starts_with($amount, '+') || $negative) {
        $amount = substr($amount, 1);
    }
    $valid = preg_match('/^\d+(\.\d{1,3})?$/D', $amount) === 1;
    if ($valid) {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $valid = strlen($whole) <= 12; // NUMERIC(15,3) storage range.
        $amount = $whole . ($fraction !== '' ? '.' . $fraction : '');
    }
    if (!$valid) {
        $amount = '0';
    } elseif (($indicator === 'DBIT' || $negative) && $amount !== '0') {
        $amount = '-' . $amount;
    }
    $party = $indicator === 'CRDT' || ($indicator === '' && !$negative) ? 'debtor' : 'creditor';
    $counterparty = ebText((string)($raw[$party]['name'] ?? ''), 140);
    $reference = trim((string)($raw['entry_reference'] ?? ''));
    $bankReference = ebText((string)($raw['reference_number'] ?? ''), 140);
    $remittance = is_array($raw['remittance_information'] ?? null) ? array_filter($raw['remittance_information'], 'is_string') : [];
    $detail = ebRemittanceText($remittance, $counterparty, $bankReference);
    // The note or the code text stands in only for a missing payment text, not for one that only repeated the payee.
    if (ebText(implode(' ', $remittance), 10000) === '') {
        $detail = (string)($raw['note'] ?? '');
        if (trim($detail) === '') {
            $detail = (string)($raw['bank_transaction_code']['description'] ?? '');
        }
    }
    $balance = is_array($raw['balance_after_transaction'] ?? null) ? $raw['balance_after_transaction'] : [];
    $balanceAmount = is_string($balance['amount'] ?? null) ? trim($balance['amount']) : '';
    $balanceCurrency = strtoupper((string)($balance['currency'] ?? ''));
    $balanceAfter = preg_match('/^-?\d{1,12}(\.\d{1,3})?$/D', $balanceAmount) === 1
        && ($balanceCurrency === '' || $balanceCurrency === strtoupper((string)($amountResource['currency'] ?? ''))) ? $balanceAmount : null;
    return ['status' => (string)($raw['status'] ?? ''), 'entry_reference' => $reference === '' ? null : $reference,
        'booking_date' => ebDate($raw['booking_date'] ?? null), 'value_date' => ebDate($raw['value_date'] ?? null),
        'transaction_date' => ebDate($raw['transaction_date'] ?? null), 'amount' => $amount,
        'currency' => mb_substr(strtoupper((string)($amountResource['currency'] ?? '')), 0, 3),
        'description' => ebText($counterparty . ' ' . $detail, 255), 'counterparty' => $counterparty ?: null,
        'bank_reference' => $bankReference ?: null, 'balance_after' => $balanceAfter, 'valid_amount' => $valid];
}

/**
 * Payment text without what the line already shows: Nordea repeats the payee name inside it
 * ("Thrusty 105023" + "Bgs Thrusty 105023"), and a piece equal to the bank reference is shown as "Ref." under the line.
 * @param array<string> $pieces remittance_information as sent by the bank.
 * @return string Collapsed text, the first copy of the payee name in each piece removed.
 */
function ebRemittanceText(array $pieces, string $counterparty, string $bankReference): string
{
    $party = ebText($counterparty, 140);
    $out = [];
    foreach ($pieces as $piece) {
        $piece = ebText($piece, 10000);
        if ($piece === '' || ($bankReference !== '' && $piece === $bankReference)) {
            continue;
        }
        $at = $party === '' ? false : mb_stripos($piece, $party, 0, 'UTF-8');
        if ($at !== false) {
            $piece = ebText(mb_substr($piece, 0, $at, 'UTF-8') . ' ' . mb_substr($piece, $at + mb_strlen($party, 'UTF-8'), null, 'UTF-8'), 10000);
        } elseif ($party !== '' && mb_stripos($party, $piece, 0, 'UTF-8') !== false) {
            $piece = '';
        }
        if ($piece !== '') {
            $out[] = $piece;
        }
    }
    return ebText(implode(' ', $out), 10000);
}

/**
 * @param array{booking_date:?string, amount:string, currency:string, description:string, counterparty:?string, value_date:?string, ...} $n
 * @return string SHA-256 occurrence identity.
 */
function ebFingerprint(array $n): string
{
    return hash('sha256', implode('|', [$n['booking_date'], $n['amount'], $n['currency'],
        mb_strtolower(ebText($n['description'], 255), 'UTF-8'), mb_strtolower($n['counterparty'] ?? '', 'UTF-8'), $n['value_date']]));
}

/**
 * @param array{booking_date:?string, value_date:?string, amount:string, currency:string, description:string, ...} $n
 * @return string SHA-256 accounting content hash.
 */
function ebPayloadHash(array $n): string
{
    return hash('sha256', implode('|', [$n['booking_date'], $n['value_date'], $n['amount'], $n['currency'], $n['description']]));
}

/**
 * @param array{valid_amount?:bool, amount:string, currency:string, booking_date:?string, date_override?:?string, review_reason?:?string, ...} $n
 * @return array{state:string, reason:?string}
 */
function ebInitialReview(array $n, ?string $importFrom): array
{
    if (($n['valid_amount'] ?? (($n['review_reason'] ?? null) !== 'bad_amount')) === false
        || preg_match('/\.\d{2}[1-9]/', (string)$n['amount'])) {
        return ['state' => 'review', 'reason' => 'bad_amount'];
    }
    if ($n['currency'] !== 'DKK') {
        return ['state' => 'review', 'reason' => 'unsupported_currency'];
    }
    $date = $n['date_override'] ?? $n['booking_date'];
    if ($date === null) {
        return ['state' => 'review', 'reason' => 'missing_date'];
    }
    if ($importFrom !== null && $date < $importFrom) {
        return ['state' => 'pre_cutover', 'reason' => null];
    }
    return ['state' => 'pending', 'reason' => null];
}
