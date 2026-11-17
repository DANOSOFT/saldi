<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebStore.php --- ver 5.1.1 --- 2026-11-17 ---
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
// 20261006 CDX/LH Created Enable Banking ebStore backend service.
// 20261007 CDX/LH Deferred incomplete no-reference days and preserved review boundaries and date overrides.
// 20261007 CDX/LH Bound stored entry references to 255 characters while hashing their full identity.
// 20261116 CL/LH Message through findtekst() (bank.account.missing).
// 20261116 CL/LH changed_payload is stored as ASCII-escaped JSON, so a LATIN9 tenant keeps characters outside ISO-8859-15 and the change hash stays stable.
// 20261117 CL/LH Reference "0" is valid; a pre-cutover line the bank re-dates past the start date becomes pending.

require_once __DIR__ . '/ebNormalize.php';

/**
 * @param list<array<string,mixed>> $normalizedWithRef
 * @param list<array<string,mixed>> $normalizedWithoutRef Complete no-reference window, never one page.
 * @return array{new:int, changed:int, review:int, skipped:int}
 */
function ebPersistTransactions(int $accountId, array $normalizedWithRef, array $normalizedWithoutRef, int $runId): array
{
    return ebPersistTransactionsChecked($accountId, $normalizedWithRef, $normalizedWithoutRef, $runId);
}

/**
 * @param list<array<string,mixed>> $normalizedWithRef
 * @param list<array<string,mixed>> $normalizedWithoutRef
 * @return array{new:int, changed:int, review:int, skipped:int}
 */
function ebPersistTransactionsChecked(int $accountId, array $normalizedWithRef, array $normalizedWithoutRef, int $runId, ?string $leaseOwner = null, ?int $connectionId = null): array
{
    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            return ebAtomic(function () use ($accountId, $normalizedWithRef, $normalizedWithoutRef, $runId, $leaseOwner, $connectionId): array {
                $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
                if (!$account) {
                    throw new EbUserError(ebT(findtekst('bank.account.missing|Bankkontoen findes ikke.', ebLang())));
                }
                if ($leaseOwner !== null && ($account['lease_owner'] !== $leaseOwner || (int)$account['connection_id'] !== $connectionId)) {
                    throw new EbLeaseLostException();
                }
                $counts = ['new' => 0, 'changed' => 0, 'review' => 0, 'skipped' => 0];
                foreach ($normalizedWithRef as $n) {
                    if ($n['status'] !== 'BOOK') {
                        $counts['skipped']++;
                        continue;
                    }
                    // "0" is a valid bank reference; only a missing one is an error.
                    if ($n['entry_reference'] === null || $n['entry_reference'] === '') {
                        throw new InvalidArgumentException('Reference row without reference');
                    }
                    $key = 'r:' . hash('sha256', $n['entry_reference']);
                    $row = ebRow('SELECT * FROM bank_transactions WHERE account_id=' . (int)$accountId . ' AND dedup_key=' . ebSql($key) . ' FOR UPDATE');
                    if (!$row) {
                        ebStoreNew($account, $n, $key, $runId, false, $counts);
                        continue;
                    }
                    $fields = ['last_seen_at' => ebNow()];
                    if ($row['payload_hash'] !== ebPayloadHash($n)) {
                        $counts['changed']++;
                        if ($row['review_state'] === 'imported' || $row['review_reason'] === 'changed_after_import') {
                            $fields += ['review_state' => 'review', 'review_reason' => 'changed_after_import',
                                'changed_payload' => json_encode($n, JSON_THROW_ON_ERROR)];
                            $counts['review']++;
                        } else {
                            $fields += ebStoredFields($n);
                            if (in_array($row['review_state'], ['ignored', 'pre_cutover'], true)) {
                                $review = ebInitialReview($n, $account['import_from']);
                                // A corrected date can move a line past the start date; a skipped line stays skipped.
                                if ($row['review_state'] === 'pre_cutover') {
                                    $fields['review_state'] = $review['state'];
                                }
                                $fields['review_reason'] = $review['reason'];
                            }
                            if (in_array($row['review_state'], ['pending', 'review'], true)) {
                                $review = ebInitialReview($n + ['date_override' => $row['date_override']], $account['import_from']);
                                if ($row['review_reason'] === 'after_review' && $review['state'] === 'pending') {
                                    $review = ['state' => 'review', 'reason' => 'after_review'];
                                }
                                $fields += ['review_state' => $review['state'], 'review_reason' => $review['reason']];
                                if ($review['state'] === 'review') {
                                    $counts['review']++;
                                }
                            }
                        }
                    }
                    ebUpdate('bank_transactions', (int)$row['id'], $fields);
                }
                $groups = [];
                foreach ($normalizedWithoutRef as $n) {
                    if ($n['status'] !== 'BOOK' || ($n['booking_date'] !== null && $n['booking_date'] >= ebToday())) {
                        $counts['skipped']++;
                        continue;
                    }
                    $groups[ebFingerprint($n)][] = $n;
                }
                foreach ($groups as $fingerprint => $occurrences) {
                    $prefix = 'f:' . $fingerprint . ':';
                    $stored = ebRows('SELECT id FROM bank_transactions WHERE account_id=' . (int)$accountId
                        . ' AND dedup_key LIKE ' . ebSql($prefix . '%') . ' FOR UPDATE');
                    $m = count($stored);
                    $n = count($occurrences);
                    ebWrite('UPDATE bank_transactions SET last_seen_at=' . ebSql(ebNow())
                        . ' WHERE account_id=' . (int)$accountId . ' AND dedup_key LIKE ' . ebSql($prefix . '%'));
                    for ($k = $m + 1; $k <= $n; $k++) {
                        ebStoreNew($account, $occurrences[$k - 1], $prefix . $k, $runId, $m > 0, $counts);
                    }
                }
                // This hint also covers two reference-bearing payments with identical content.
                $duplicates = ebRows('SELECT fingerprint FROM bank_transactions WHERE account_id=' . (int)$accountId . ' GROUP BY fingerprint HAVING COUNT(*)>1');
                foreach ($duplicates as $duplicate) {
                    ebWrite('UPDATE bank_transactions SET identical_group=1 WHERE account_id=' . (int)$accountId . ' AND fingerprint=' . ebSql($duplicate['fingerprint']));
                }
                return $counts;
            });
        } catch (EbDbException $e) {
            if (!$e->uniqueViolation || $attempt === 1) {
                throw $e;
            }
        }
    }
    throw new EbDbException();
}

/**
 * @param array<string,mixed> $n
 * @return array<string,string|null> Whitelisted persisted normalized fields.
 */
function ebStoredFields(array $n): array
{
    $out = [];
    foreach (['entry_reference', 'booking_date', 'value_date', 'transaction_date', 'amount', 'currency',
        'description', 'counterparty', 'bank_reference', 'status'] as $field) {
        $out[$field] = $n[$field];
    }
    // The dedup key above hashes the full reference; only its display/storage copy is bounded.
    $out['entry_reference'] = $n['entry_reference'] === null ? null : mb_substr($n['entry_reference'], 0, 255, 'UTF-8');
    $out['fingerprint'] = ebFingerprint($n);
    $out['payload_hash'] = ebPayloadHash($n);
    return $out;
}

/**
 * @param array<string,string|null> $account
 * @param array<string,mixed> $n
 * @param array{new:int, changed:int, review:int, skipped:int} $counts
 * @return void
 */
function ebStoreNew(array $account, array $n, string $key, int $runId, bool $possibleDuplicate, array &$counts): void
{
    $review = ebInitialReview($n, $account['import_from']);
    if ($possibleDuplicate && $review['state'] === 'pending') {
        $review = ['state' => 'review', 'reason' => 'possible_duplicate'];
    }
    ebInsert('bank_transactions', ebStoredFields($n) + ['account_id' => (int)$account['id'], 'dedup_key' => $key,
        'review_state' => $review['state'], 'review_reason' => $review['reason'],
        'first_seen_at' => ebNow(), 'last_seen_at' => ebNow(), 'first_run_id' => $runId ?: null]);
    $counts['new']++;
    if ($review['state'] === 'review') {
        $counts['review']++;
    }
}
