<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebSync.php --- ver 5.1.1 --- 2026-11-17 ---
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
// 20261006 CDX/LH Created Enable Banking ebSync backend service.
// 20261007 CDX/LH Scheduled due manual fetches six hours later and returned error codes and kinds.
// 20261028 CDX/LH Fetch active accounts and deliver local automatic queues between bank calls.
// 20261028 CDX/LH Stop retries on period errors and expose the worker heartbeat age.
// 20261116 CL/LH Pull model: a fetch only stores lines; nothing is delivered to a journal (ebDeliverQueued() removed).
// 20261117 CL/LH ebSyncAccount($onlyIfDue): the worker re-checks under the lease that the account is still due.

require_once __DIR__ . '/ebClient.php';
require_once __DIR__ . '/ebJournal.php';
require_once __DIR__ . '/crypt.php';

/**
 * @param array<string,string> $psuHeaders
 * @param bool $onlyIfDue Worker run without --force: skip an account that is no longer due when its lease is taken.
 * @return array{status:string, run_id:int, message:string, error_code?:?string, error_kind?:?string}
 */
function ebSyncAccount(int $accountId, string $trigger, array $psuHeaders = [], ?EnableBankingClient $client = null, bool $onlyIfDue = false): array
{
    if (!in_array($trigger, ['schedule', 'manual'], true)) {
        throw new InvalidArgumentException('Invalid sync trigger');
    }
    $owner = bin2hex(random_bytes(24));
    $account = ebAtomic(function () use ($accountId, $owner, $trigger, $onlyIfDue): ?array {
        $row = ebRow('SELECT * FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
        if (!$row) {
            throw new EbUserError(ebT(findtekst('bank.account.missing|Bankkontoen findes ikke.', ebLang())));
        }
        if ($row['state'] !== 'active' || !ebCompanyEnabled()) {
            return null;
        }
        if ($row['lease_until'] !== null && $row['lease_until'] > ebNow()) {
            return null;
        }
        // The worker lists due accounts before it fetches them; a "Hent nu" meanwhile moves the next fetch, so check again here (same rule as ebDueAccounts()).
        if ($onlyIfDue && !($row['next_fetch_at'] !== null ? $row['next_fetch_at'] <= ebNow()
            : $row['last_fetch_at'] === null && $row['last_error_code'] === null)) {
            return null;
        }
        if ($trigger === 'manual' && $row['last_manual_at'] !== null && strtotime($row['last_manual_at']) > time() - 300) {
            return null;
        }
        $fields = ['lease_owner' => $owner, 'lease_until' => date('Y-m-d H:i:s', time() + 900)];
        if ($trigger === 'manual') {
            $fields['last_manual_at'] = ebNow();
        }
        ebUpdate('bank_accounts', $accountId, $fields);
        return $row;
    });
    if ($account === null) {
        return ['status' => 'skipped', 'run_id' => 0, 'message' => ebT(findtekst('bank.fetch.busy|En hentning er allerede i gang eller er netop udført.', ebLang()))];
    }
    $runId = 0;
    $stats = ['pages' => 0, 'fetched' => 0, 'new_count' => 0, 'changed_count' => 0, 'imported_count' => 0, 'review_count' => 0];
    $status = 'failed';
    $message = '';
    $errorCode = null;
    $errorKind = null;
    try {
        $runId = ebInsert('bank_sync_runs', ['account_id' => $accountId, 'trigger' => $trigger, 'started_at' => ebNow(), 'status' => 'running']);
        if ($trigger === 'manual') {
            ebAudit($GLOBALS['brugernavn'] ?? 'worker', 'refresh', $accountId);
        }
        $connection = ebRow('SELECT * FROM bank_connections WHERE id=' . (int)$account['connection_id']);
        if (!ebCompanyEnabled() || !in_array($account['state'], ['active'], true)
            || !$account['ledger_account'] || !$account['import_from']) {
            $status = 'skipped';
            $message = ebT(findtekst('bank.fetch.not_ready|Bankkontoen er ikke klar til hentning.', ebLang()));
        } elseif (!$connection || $connection['status'] !== 'active' || $connection['valid_until'] === null || $connection['valid_until'] <= ebNow()) {
            throw new EbApiException(401, 'EXPIRED_SESSION', 'Bankadgangen er udløbet. Forny adgang.');
        } else {
            $session = ebRow('SELECT account_uid FROM bank_account_sessions WHERE account_id=' . (int)$accountId
                . ' AND connection_id=' . (int)$account['connection_id'] . ' AND active=1');
            if (!$session || !$session['account_uid']) {
                throw new EbApiException(404, 'ACCOUNT_DOES_NOT_EXIST', 'Bankkontoen skal forbindes igen.');
            }
            $to = ebToday();
            $from = $account['fetched_through'] === null ? $account['import_from']
                : max($account['import_from'], date('Y-m-d', strtotime($account['fetched_through'] . ' -10 days')));
            if ($from > $to) {
                throw new EbUserError(ebT(findtekst('bank.fetch.start_in_future|Startdatoen ligger i fremtiden.', ebLang())));
            }
            ebUpdate('bank_sync_runs', $runId, ['window_from' => $from, 'window_to' => $to]);
            $client = $client ?? new EnableBankingClient(ebConfig());
            if ($trigger !== 'manual') {
                $psuHeaders = [];
            } elseif ($psuHeaders !== []) {
                // Metadata lookup is outside every DB transaction; unknown bank means no PSU headers.
                $banks = $client->aspsps($connection['aspsp_country'], $connection['psu_type']);
                $matched = false;
                foreach ($banks as $bank) {
                    if ($bank['name'] === $connection['aspsp_name']) {
                        $psuHeaders = ebFilterPsuHeaders($psuHeaders, $bank['required_psu_headers'] ?? []);
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $psuHeaders = [];
                }
            }
            $key = null;
            $buffer = [];
            $restarted = false;
            while (true) {
                if ($stats['pages'] >= 1000) {
                    throw new EbApiException(200, 'PAGE_CAP', 'Bankhentningen nåede sidegrænsen.');
                }
                // Renew the lease between bounded calls; a large paginated run can exceed 15 minutes.
                ebSyncUpdate($accountId, $owner, (int)$account['connection_id'], ['lease_until' => date('Y-m-d H:i:s', time() + 900)]);
                try {
                    $page = $client->transactions($session['account_uid'], $from, $to, $key, $psuHeaders);
                } catch (EbApiException $e) {
                    if ($e->kind() === 'continuation' && !$restarted) {
                        $restarted = true;
                        $key = null;
                        $buffer = [];
                        continue;
                    }
                    throw $e;
                }
                $stats['pages']++;
                $stats['fetched'] += count($page['transactions']);
                $refs = [];
                foreach ($page['transactions'] as $raw) {
                    $n = ebNormalizeTransaction($raw);
                    if ($n['entry_reference'] !== null || $n['status'] !== 'BOOK') {
                        $refs[] = $n;
                    } else {
                        $buffer[] = $n;
                    }
                }
                ebAddSyncCounts($stats, ebPersistTransactionsChecked($accountId, $refs, [], $runId, $owner, (int)$account['connection_id']));
                $key = $page['continuation_key'];
                if ($key === null || $key === '') {
                    break;
                }
            }
            ebAddSyncCounts($stats, ebPersistTransactionsChecked($accountId, [], $buffer, $runId, $owner, (int)$account['connection_id']));
            $fields = ['fetched_through' => $to, 'last_fetch_at' => ebNow(), 'fail_count' => 0,
                'last_error_code' => null, 'last_error' => null, 'updated_at' => ebNow()];
            if ($trigger !== 'manual' || $account['next_fetch_at'] === null || $account['next_fetch_at'] <= ebNow()) {
                $fields['next_fetch_at'] = date('Y-m-d H:i:s', time() + 21600);
            }
            ebSyncUpdate($accountId, $owner, (int)$account['connection_id'], $fields);
            ebReconcileLinks($accountId);
            $status = 'ok';
            $stats['review_count'] = (int)ebRow('SELECT COUNT(*) AS n FROM bank_transactions WHERE account_id=' . (int)$accountId . " AND review_state='review'")['n'];
        }
    } catch (EbLeaseLostException $e) {
        $status = 'skipped';
        $message = ebT(findtekst('bank.fetch.lease_lost|Bankforbindelsen blev ændret under hentningen.', ebLang()));
    } catch (Throwable $e) {
        try {
            ebAtomic(function () use ($e, $accountId, $account, $owner, $trigger, &$status, &$errorCode, &$errorKind, &$message): void {
                ebRows('SELECT id FROM bank_connections WHERE id=' . (int)$account['connection_id'] . ' FOR UPDATE');
                $current = ebRow('SELECT lease_owner, connection_id FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
                if (!$current || $current['lease_owner'] !== $owner || (int)$current['connection_id'] !== (int)$account['connection_id']) {
                    throw new EbLeaseLostException();
                }
                $errorCode = $e instanceof EbApiException ? $e->errorCode : ($e instanceof EbDbException ? 'database_error' : 'internal_error');
                $message = $e instanceof EbApiException ? $e->getMessage() : 'Bankhentningen mislykkedes. Prøv igen senere.';
                $kind = $e instanceof EbApiException ? $e->kind() : 'transient';
                $errorKind = $kind;
                $status = $errorCode === 'PAGE_CAP' ? 'partial' : 'failed';
                $fields = ['last_error_code' => $errorCode, 'last_error' => $message, 'updated_at' => ebNow()];
                if (in_array($kind, ['consent', 'action_required', 'period'], true)) {
                    $fields['next_fetch_at'] = null;
                    if ($kind === 'consent') {
                        $status = 'consent_expired';
                        ebUpdate('bank_connections', (int)$account['connection_id'], ['status' => $errorCode === 'REVOKED_SESSION' ? 'revoked' : 'expired',
                            'last_error' => $message, 'updated_at' => ebNow()]);
                        // All accounts on this consent must stop scheduling.
                        ebWrite('UPDATE bank_accounts SET next_fetch_at=NULL, last_error_code=' . ebSql('consent_expired')
                            . ', last_error=' . ebSql($message) . ' WHERE connection_id=' . (int)$account['connection_id']);
                        $fields['last_error_code'] = 'consent_expired';
                    }
                } elseif ($kind === 'rate_limit') {
                    $status = 'rate_limited';
                    if ($trigger !== 'manual') {
                        $fields['next_fetch_at'] = date('Y-m-d H:i:s', time() + 21600);
                    }
                } elseif ($kind === 'transient') {
                    $fail = min(30, (int)$account['fail_count'] + 1);
                    $fields['fail_count'] = $fail;
                    if ($trigger !== 'manual') {
                        $fields['next_fetch_at'] = date('Y-m-d H:i:s', time() + min(21600, 1800 * (2 ** min(5, $fail - 1))));
                    }
                } elseif ($trigger !== 'manual') {
                    $fields['next_fetch_at'] = date('Y-m-d H:i:s', time() + 21600);
                }
                ebUpdate('bank_accounts', $accountId, $fields);
            });
        } catch (EbLeaseLostException $lost) {
            $status = 'skipped';
            $errorCode = null;
            $message = ebT(findtekst('bank.fetch.lease_lost|Bankforbindelsen blev ændret under hentningen.', ebLang()));
        }
    } finally {
        try {
            if ($runId > 0) {
                ebUpdate('bank_sync_runs', $runId, $stats + ['finished_at' => ebNow(), 'status' => $status,
                    'error_code' => $errorCode, 'error_message' => $errorCode === null ? null : $message]);
            }
        } finally {
            ebWrite('UPDATE bank_accounts SET lease_owner=NULL, lease_until=NULL WHERE id=' . (int)$accountId . ' AND lease_owner=' . ebSql($owner));
        }
    }
    return ['status' => $status, 'run_id' => $runId, 'message' => $message, 'error_code' => $errorCode, 'error_kind' => $errorKind];
}

/**
 * @param array{pages:int, fetched:int, new_count:int, changed_count:int, imported_count:int, review_count:int} $stats
 * @param array{new:int, changed:int, review:int, skipped:int} $counts
 * @return void
 */
function ebAddSyncCounts(array &$stats, array $counts): void
{
    $stats['new_count'] += $counts['new'];
    $stats['changed_count'] += $counts['changed'];
    $stats['review_count'] += $counts['review'];
}

/** @return int|null Seconds since the parent worker heartbeat, or null when missing or invalid. */
function ebWorkerHeartbeatAge(): ?int
{
    $path = __DIR__ . '/../../temp/.ht_bank_worker.heartbeat';
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $timestamp = trim((string)file_get_contents($path));
    return ctype_digit($timestamp) ? max(0, time() - (int)$timestamp) : null;
}

/** @return list<int> Eligible scheduled ids, excluding stopped consent, action-required and period errors. */
function ebDueAccounts(): array
{
    $rows = ebRows("SELECT a.id FROM bank_accounts a JOIN bank_connections c ON c.id=a.connection_id
        WHERE a.state='active' AND c.status='active'
        AND (a.next_fetch_at <= " . ebSql(ebNow()) . " OR (a.next_fetch_at IS NULL AND a.last_fetch_at IS NULL AND a.last_error_code IS NULL)) ORDER BY a.id");
    return array_map(static fn(array $r): int => (int)$r['id'], $rows);
}

/** @return void Make a newly mapped account eligible for its first scheduled window. */
function ebScheduleAfterMapping(int $accountId): void
{
    ebUpdate('bank_accounts', $accountId, ['next_fetch_at' => ebNow(), 'updated_at' => ebNow()]);
}

/**
 * Fence in-flight sync writes after disconnect/reconnect or lease replacement.
 * @param array<string,string|int|null> $fields
 * @return void
 */
function ebSyncUpdate(int $accountId, string $leaseOwner, int $connectionId, array $fields): void
{
    ebAtomic(function () use ($accountId, $leaseOwner, $connectionId, $fields): void {
        $account = ebRow('SELECT lease_owner, connection_id FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
        if (!$account || $account['lease_owner'] !== $leaseOwner || (int)$account['connection_id'] !== $connectionId) {
            throw new EbLeaseLostException();
        }
        ebUpdate('bank_accounts', $accountId, $fields);
    });
}
