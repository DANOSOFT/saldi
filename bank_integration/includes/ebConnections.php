<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebConnections.php --- ver 5.1.1 --- 2026-11-17 ---
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
// 20261006 CDX/LH Created Enable Banking ebConnections backend service.
// 20261007 CDX/LH Prevented account merges, supported cutover remapping and completed UI projections.
// 20261007 CDX/LH Bound first approval to reviewed rows, paginated onboarding and required DKK base currency.
// 20261007 CDX/LH Fingerprinted frozen first reviews, validated skips and normalized account names for mysqli.
// 20261022 CDX/LH Exclude proven ambiguous hashes, foreign journals and closed ledger accounts.
// 20261028 CDX/LH Activate mapped accounts and add auto import, pause, bounded hints and revoke feedback.
// 20261028 CDX/LH Reset corrected period errors and report manual waiting rows and fetch age.
// 20261028 CL/LH Plain Danish overlap hint for the pick list.
// 20261028 CDX/LH Preserve stopped consent, action-required and period errors when resuming fetching.
// 20261028 CL/LH ebSetPaused reports whether a fetch is scheduled; revoke warning shows only a future expiry date.
// 20261116 CL/LH Pull model: no automatic import switch, journal pointer or deferral in the account status.
// 20261117 CL/LH A consent that cannot be stored is closed at the bank again; overlap hints use the accounting date (date_override first).
//                Messages through findtekst() (bank.* keys).

require_once __DIR__ . '/ebSync.php';

/** @return string Provider authorization URL; only a hash of the callback state is stored locally. */
function ebStartAuthorization(EnableBankingClient $c, string $aspspName, string $country, string $psuType, string $purpose, ?int $connectionId, int $userId, string $username): string
{
    if (!ebCompanyEnabled() || !in_array($purpose, ['connect', 'reconnect'], true)) {
        throw new EbUserError(ebT(findtekst('bank.connect.not_enabled|Bankforbindelsen er ikke aktiveret.', ebLang())));
    }
    if ($purpose === 'reconnect' && ($connectionId === null || !ebRow('SELECT id FROM bank_connections WHERE id=' . (int)$connectionId))) {
        throw new EbUserError(ebT(findtekst('bank.connection.missing|Bankforbindelsen findes ikke i dette regnskab.', ebLang())));
    }
    $bank = null;
    foreach ($c->aspsps($country, $psuType) as $candidate) {
        if ($candidate['name'] === $aspspName && $candidate['country'] === $country && in_array($psuType, $candidate['psu_types'], true)) {
            $bank = $candidate;
            break;
        }
    }
    if ($bank === null) {
        throw new EbUserError(ebT(findtekst('bank.connect.bank_unavailable|Den valgte bank eller kundetype er ikke tilgængelig.', ebLang())));
    }
    $duration = min((int)($bank['maximum_consent_validity'] ?? 15552000), 15552000) - 3600;
    if ($duration <= 0) {
        throw new EbUserError(ebT(findtekst('bank.connect.access_too_short|Bankens adgangsperiode er for kort.', ebLang())));
    }
    $state = bin2hex(random_bytes(32));
    $stateId = ebInsert('bank_auth_states', ['state_hash' => hash('sha256', $state), 'user_id' => $userId, 'username' => $username,
        'environment' => ebConfig()['environment'], 'purpose' => $purpose, 'connection_id' => $purpose === 'reconnect' ? $connectionId : null,
        'aspsp_name' => $aspspName, 'aspsp_country' => $country, 'psu_type' => $psuType, 'created_at' => ebNow(),
        'expires_at' => date('Y-m-d H:i:s', time() + 1800)]);
    try {
        $response = $c->startAuth($aspspName, $country, $psuType, $state, date(DATE_RFC3339, time() + $duration));
    } catch (Throwable $e) {
        ebUpdate('bank_auth_states', $stateId, ['used_at' => ebNow()]);
        throw $e;
    }
    ebAudit($username, 'connect_started', null, $connectionId);
    return $response['url'];
}

/** @return array{id:string, state_hash:string, user_id:string, username:string, environment:string, purpose:string, connection_id:?string, aspsp_name:string, aspsp_country:string, psu_type:string, created_at:string, expires_at:string, used_at:?string} */
function ebConsumeAuthorization(string $state, int $userId): array
{
    return ebAtomic(function () use ($state, $userId): array {
        $row = ebRow('SELECT * FROM bank_auth_states WHERE state_hash=' . ebSql(hash('sha256', $state)) . ' FOR UPDATE');
        if (!$row) {
            throw new EbUserError(ebT(findtekst('bank.auth.other_company|Godkendelsen findes ikke i dette regnskab. Start igen fra det rigtige regnskab.', ebLang())));
        }
        if ((int)$row['user_id'] !== $userId) {
            throw new EbUserError(ebT(findtekst('bank.auth.other_user|Godkendelsen tilhører en anden bruger.', ebLang())));
        }
        if ($row['used_at'] !== null) {
            throw new EbUserError(ebT(findtekst('bank.auth.used|Godkendelsen er allerede brugt. Start en ny forbindelse.', ebLang())));
        }
        if ($row['expires_at'] <= ebNow()) {
            throw new EbUserError(ebT(findtekst('bank.auth.expired|Godkendelsen er udløbet. Start igen.', ebLang())));
        }
        ebUpdate('bank_auth_states', (int)$row['id'], ['used_at' => ebNow()]);
        $row['used_at'] = ebNow();
        return $row;
    });
}

/**
 * @param array{id:string|int, environment:string, purpose:string, connection_id:string|int|null, aspsp_name:string, aspsp_country:string, psu_type:string, ...} $stateRow
 * @return int New connection id; alias matches preserve local account identity.
 */
function ebCompleteAuthorization(EnableBankingClient $c, array $stateRow, string $code, string $username): int
{
    // The authorization code and encryption operations are outside the persistence transaction.
    $storedState = ebRow('SELECT * FROM bank_auth_states WHERE id=' . (int)$stateRow['id']);
    if (!$storedState || $storedState['used_at'] === null || $storedState['username'] !== $username) {
        throw new EbUserError(ebT(findtekst('bank.auth.not_validated|Godkendelsen skal valideres først.', ebLang())));
    }
    $stateRow = $storedState;
    $session = $c->createSession($code);
    $encrypted = oauthEncrypt($session['session_id']);
    $old = $stateRow['connection_id'] ? ebRow('SELECT * FROM bank_connections WHERE id=' . (int)$stateRow['connection_id']) : null;
    // The consent exists at the bank from here on. If storing it fails, close it again, so no consent stays valid that Saldi cannot see or revoke.
    $stored = false;
    try {
        $newId = ebNamedLock('connections', function () use ($stateRow, $session, $encrypted, $username, &$stored): int {
            $id = ebAtomic(function () use ($stateRow, $session, $encrypted, $username): int {
                $id = ebInsert('bank_connections', ['provider' => 'enablebanking', 'environment' => $stateRow['environment'],
                    'aspsp_name' => $stateRow['aspsp_name'], 'aspsp_country' => $stateRow['aspsp_country'], 'psu_type' => $stateRow['psu_type'],
                    'session_enc' => $encrypted, 'session_hash' => hash('sha256', $session['session_id']), 'status' => 'active',
                    'valid_until' => date('Y-m-d H:i:s', strtotime($session['access']['valid_until'])), 'created_by' => $username,
                    'created_at' => ebNow(), 'updated_at' => ebNow()]);
                $hashCounts = [];
                foreach ($session['accounts'] as $resource) {
                    $hashes = array_unique(array_merge([$resource['identification_hash']], $resource['identification_hashes'] ?? []));
                    foreach ($hashes as $hash) {
                        if (!is_string($hash) || $hash === '' || strlen($hash) > 255) {
                            throw new EbUserError(ebT(findtekst('bank.auth.bad_identity|Bankens kontoidentifikation er ugyldig.', ebLang())));
                        }
                        $hashCounts[$hash] = ($hashCounts[$hash] ?? 0) + 1;
                    }
                }
                $ambiguous = [];
                if ($hashCounts !== []) {
                    $values = implode(',', array_map(static fn($hash): string => ebSql((string)$hash), array_keys($hashCounts)));
                    foreach (ebRows('SELECT identification_hash FROM bank_ambiguous_hashes WHERE identification_hash IN (' . $values . ')') as $row) {
                        $ambiguous[$row['identification_hash']] = true;
                    }
                }
                foreach ($hashCounts as $hash => $count) {
                    if ($count > 1) {
                        // Once proven ambiguous, a hash must never identify an account in a later consent.
                        if (!isset($ambiguous[$hash])) {
                            ebInsert('bank_ambiguous_hashes', ['identification_hash' => (string)$hash, 'created_at' => ebNow()]);
                            $ambiguous[$hash] = true;
                        }
                        ebWrite('DELETE FROM bank_account_aliases WHERE identification_hash=' . ebSql((string)$hash));
                        foreach (ebRows('SELECT id FROM bank_accounts WHERE identification_hash=' . ebSql((string)$hash)) as $row) {
                            ebUpdate('bank_accounts', (int)$row['id'], ['identification_hash' => 'local:' . bin2hex(random_bytes(32))]);
                        }
                    }
                }
                $claimed = [];
                foreach ($session['accounts'] as $resource) {
                    if (empty($resource['uid'])) {
                        continue;
                    }
                    $hashes = array_values(array_unique(array_merge([$resource['identification_hash']], $resource['identification_hashes'] ?? [])));
                    $hashes = array_values(array_filter($hashes, static fn(string $hash): bool => $hashCounts[$hash] === 1 && !isset($ambiguous[$hash])));
                    $primary = $resource['identification_hash'];
                    $currency = strtoupper($resource['currency']);
                    $matches = [];
                    if (in_array($primary, $hashes, true)) {
                        $matches = ebRows('SELECT DISTINCT a.* FROM bank_accounts a LEFT JOIN bank_account_aliases h ON h.account_id=a.id'
                            . ' WHERE a.identification_hash=' . ebSql($primary) . ' OR h.identification_hash=' . ebSql($primary));
                    }
                    $account = count($matches) === 1 ? $matches[0] : null;
                    if ($matches === [] && $hashes !== []) {
                        $secondary = array_values(array_diff($hashes, [$primary]));
                        if ($secondary !== []) {
                            $values = implode(',', array_map('ebSql', $secondary));
                            $matches = ebRows('SELECT DISTINCT a.* FROM bank_accounts a LEFT JOIN bank_account_aliases h ON h.account_id=a.id'
                                . ' WHERE a.identification_hash IN (' . $values . ') OR h.identification_hash IN (' . $values . ')');
                            if (count($matches) === 1 && $matches[0]['currency'] === $currency) {
                                $account = $matches[0];
                            }
                        }
                    }
                    if ($account && isset($claimed[(int)$account['id']])) {
                        $account = null;
                    }
                    $name = $resource['name'] ?? $resource['details'] ?? $resource['product'] ?? '';
                    $fields = ['connection_id' => $id, 'display_name' => ebText(ebBackendText((string)$name, ebMysql()), 200),
                        'display_identity' => ebMaskedIdentity($resource), 'currency' => $currency,
                        'cash_account_type' => mb_substr((string)($resource['cash_account_type'] ?? ''), 0, 4), 'updated_at' => ebNow()];
                    if ($account) {
                        $accountId = (int)$account['id'];
                        if ($currency !== 'DKK') {
                            $fields['state'] = 'unsupported';
                            $fields['next_fetch_at'] = null;
                        } elseif (in_array($account['state'], ['disconnected', 'unsupported'], true)) {
                            $fields['state'] = $account['ledger_account'] && $account['import_from']
                                ? 'active' : 'new';
                        }
                        if ($currency === 'DKK' && in_array($fields['state'] ?? $account['state'], ['active'], true)) {
                            $fields['next_fetch_at'] = ebNow();
                        }
                        $fields += ['last_error_code' => null, 'last_error' => null, 'fail_count' => 0, 'lease_owner' => null, 'lease_until' => null];
                        ebUpdate('bank_accounts', $accountId, $fields);
                        ebWrite('UPDATE bank_account_sessions SET active=0 WHERE account_id=' . $accountId);
                    } else {
                        // Non-identifying or already-owned primaries need an independent local identity.
                        $identity = $primary;
                        if (!in_array($primary, $hashes, true) || ebRow('SELECT id FROM bank_accounts WHERE identification_hash=' . ebSql($primary))) {
                            $identity = 'local:' . bin2hex(random_bytes(32));
                        }
                        $accountId = ebInsert('bank_accounts', $fields + ['identification_hash' => $identity,
                            'state' => $currency === 'DKK' ? 'new' : 'unsupported', 'created_at' => ebNow()]);
                    }
                    $claimed[$accountId] = true;
                    foreach ($hashes as $hash) {
                        if (!ebRow('SELECT id FROM bank_accounts WHERE identification_hash=' . ebSql($hash) . ' AND id<>' . $accountId)
                            && !ebRow('SELECT id FROM bank_account_aliases WHERE identification_hash=' . ebSql($hash))) {
                            ebInsert('bank_account_aliases', ['account_id' => $accountId, 'identification_hash' => $hash]);
                        }
                    }
                    ebInsert('bank_account_sessions', ['account_id' => $accountId, 'connection_id' => $id,
                        'account_uid' => $resource['uid'], 'active' => 1, 'created_at' => ebNow()]);
                }
                if ($stateRow['purpose'] === 'reconnect' && $stateRow['connection_id']) {
                    $oldId = (int)$stateRow['connection_id'];
                    ebUpdate('bank_connections', $oldId, ['status' => 'replaced', 'updated_at' => ebNow()]);
                    // Accounts absent from the renewed consent can no longer fetch on the replaced session.
                    ebWrite("UPDATE bank_accounts SET state='disconnected', next_fetch_at=NULL, lease_owner=NULL, lease_until=NULL WHERE connection_id=" . $oldId);
                    ebWrite('UPDATE bank_account_sessions SET active=0 WHERE connection_id=' . $oldId);
                }
                ebAudit($username, $stateRow['purpose'] === 'reconnect' ? 'reconnected' : 'connected', null, $id);
                return $id;
            });
            $stored = true;
            return $id;
        });
    } catch (Throwable $e) {
        if ($stored) {
            // Committed; only releasing the lock failed. The connection is stored, so the consent stays.
            throw $e;
        }
        try {
            $c->deleteSession($session['session_id']);
        } catch (Throwable $ignored) {
            // Best effort: the original error is the one the user needs.
        }
        throw $e;
    }
    if ($old) {
        ebCloseProviderSession($old, $c);
    }
    return $newId;
}

/**
 * @param array{account_id?:array{iban?:string, other?:array{identification?:string}}, ...} $resource
 * @return string Masked display identity; never store an unmasked IBAN.
 */
function ebMaskedIdentity(array $resource): string
{
    $iban = preg_replace('/\s+/', '', (string)($resource['account_id']['iban'] ?? ''));
    if ($iban !== '' && strlen($iban) >= 8) {
        return substr($iban, 0, 4) . ' **** **** ' . substr($iban, -4);
    }
    $other = (string)($resource['account_id']['other']['identification'] ?? '');
    return $other === '' ? '' : '**** ' . substr($other, -4);
}

/**
 * @param array{session_enc:string, ...} $connection
 * @return bool Whether the provider confirmed revocation outside local transactions.
 */
function ebCloseProviderSession(array $connection, ?EnableBankingClient $c): bool
{
    if ($c === null) {
        return false;
    }
    try {
        $session = oauthDecrypt($connection['session_enc']);
        if ($session === null) {
            throw new RuntimeException('Unavailable session');
        }
        $c->deleteSession($session);
        return true;
    } catch (Throwable $e) {
        error_log('Enable Banking: provider session closure failed; local disconnection retained.');
        return false;
    }
}

/** @return void Disconnect consent and scheduling while keeping accounting records and mappings. */
function ebDisconnect(int $connectionId, string $username, ?EnableBankingClient $c): void
{
    $connection = ebAtomic(function () use ($connectionId, $username): array {
        $row = ebRow('SELECT * FROM bank_connections WHERE id=' . (int)$connectionId . ' FOR UPDATE');
        if (!$row) {
            throw new EbUserError(ebT(findtekst('bank.connection.missing|Bankforbindelsen findes ikke i dette regnskab.', ebLang())));
        }
        ebUpdate('bank_connections', $connectionId, ['status' => 'disconnected', 'disconnected_at' => ebNow(),
            'disconnected_by' => $username, 'updated_at' => ebNow()]);
        ebWrite("UPDATE bank_accounts SET state='disconnected', next_fetch_at=NULL, lease_owner=NULL, lease_until=NULL WHERE connection_id=" . (int)$connectionId);
        ebWrite('UPDATE bank_account_sessions SET active=0 WHERE connection_id=' . (int)$connectionId);
        ebAudit($username, 'disconnected', null, $connectionId);
        return $row;
    });
    $closed = ebCloseProviderSession($connection, $c);
    $message = ebT(findtekst('bank.connection.revoke_unconfirmed|Banken bekræftede ikke afbrydelsen.', ebLang()))
        . ($connection['valid_until'] === null || strtotime($connection['valid_until']) <= time() ? ''
            : ' ' . sprintf(ebT(findtekst('bank.connection.expires_at_latest|Adgangen udløber senest %s.', ebLang())), date('d.m.Y', strtotime($connection['valid_until']))));
    ebUpdate('bank_connections', $connectionId, ['last_error' => $closed ? null : $message]);
}

/** @return void Validate ledger mapping and cutover before activating automatic fetching. */
function ebMapAccount(int $accountId, int $ledgerAccount, string $importFrom, string $username): void
{
    if (ebBaseCurrency() !== 'DKK') {
        throw new EbUserError(ebT(findtekst('bank.accounts.base_currency|Bankimport kræver, at regnskabets basisvaluta er DKK.', ebLang())));
    }
    if (!ebDate($importFrom) || $importFrom > ebToday()) {
        throw new EbUserError(ebT(findtekst('bank.accounts.start_invalid|Startdatoen skal være en gyldig dato og må ikke ligge i fremtiden.', ebLang())));
    }
    $regnaar = (int)($GLOBALS['regnaar'] ?? 0);
    if (!ebRow('SELECT kontonr FROM kontoplan WHERE kontonr=' . (int)$ledgerAccount . ' AND regnskabsaar=' . $regnaar . " AND kontotype IN ('D','S') AND (lukket IS NULL OR lukket<>'on')")) {
        throw new EbUserError(ebT(findtekst('bank.accounts.ledger_closed|Vælg en åben finanskonto i det aktuelle regnskabsår.', ebLang())));
    }
    ebAtomic(function () use ($accountId, $ledgerAccount, $importFrom, $username): void {
        $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
        if (!$account || $account['currency'] !== 'DKK' || !in_array($account['state'], ['new', 'active', 'paused'], true)) {
            throw new EbUserError(ebT(findtekst('bank.accounts.not_mappable|Bankkontoen kan ikke tilknyttes. Kun DKK understøttes.', ebLang())));
        }
        $fields = ['ledger_account' => $ledgerAccount, 'import_from' => $importFrom,
            'state' => $account['state'] === 'new' ? 'active' : $account['state'], 'updated_at' => ebNow()];
        $periodError = in_array($account['last_error_code'], ['WRONG_TRANSACTIONS_PERIOD', 'WRONG_DATE_INTERVAL'], true);
        if ($account['state'] !== 'paused' && (!$periodError || $account['import_from'] !== $importFrom)) {
            $fields['next_fetch_at'] = ebNow();
        }
        if ($account['import_from'] !== $importFrom && $periodError) {
            $fields['last_error_code'] = null;
            $fields['last_error'] = null;
        }
        if ((int)$account['ledger_account'] !== $ledgerAccount || $account['import_from'] !== $importFrom) {
            // Fence a fetch using the old cutover so it cannot overwrite the reset checkpoint.
            $fields += ['lease_owner' => null, 'lease_until' => null, 'last_manual_at' => null];
        }
        if ($account['import_from'] !== null && $importFrom < $account['import_from']) {
            $fields['fetched_through'] = null;
            foreach (ebRows('SELECT * FROM bank_transactions WHERE account_id=' . (int)$accountId
                . " AND review_state='pre_cutover' AND booking_date>=" . ebSql($importFrom) . ' FOR UPDATE') as $tx) {
                $review = ebInitialReview($tx, $importFrom);
                ebUpdate('bank_transactions', (int)$tx['id'], ['review_state' => $review['state'], 'review_reason' => $review['reason']]);
            }
        } elseif ($account['import_from'] !== null && $importFrom > $account['import_from']) {
            ebWrite("UPDATE bank_transactions SET review_state='pre_cutover', review_reason=NULL WHERE account_id=" . (int)$accountId
                . " AND review_state='pending' AND booking_date<" . ebSql($importFrom));
        }
        ebUpdate('bank_accounts', $accountId, $fields);
        ebAudit($username, 'mapped', $accountId, null, 'Ledger ' . (int)$account['ledger_account'] . ' -> ' . $ledgerAccount
            . '; start ' . $account['import_from'] . ' -> ' . $importFrom);
    });
}

/** @return bool Whether a fetch is scheduled after the change (false while paused or when access must be renewed first). */
function ebSetPaused(int $accountId, bool $paused, string $username): bool
{
    return ebAtomic(function () use ($accountId, $paused, $username): bool {
        $account = ebRow('SELECT state, connection_id, last_error_code FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
        if (!$account || $account['state'] !== ($paused ? 'active' : 'paused')) {
            throw new EbUserError(ebT(findtekst('bank.feed.pause_not_allowed|Bankkontoen kan ikke sættes på pause eller genoptages i den nuværende tilstand.', ebLang())));
        }
        $nextFetch = null;
        if (!$paused) {
            $connection = ebRow('SELECT status FROM bank_connections WHERE id=' . (int)$account['connection_id']);
            $code = strtoupper((string)$account['last_error_code']);
            $kind = (new EbApiException(400, $code, ''))->kind();
            if ($connection && $connection['status'] === 'active'
                && !in_array($kind, ['consent', 'action_required', 'period'], true)) {
                $nextFetch = ebNow();
            }
        }
        ebUpdate('bank_accounts', $accountId, ['state' => $paused ? 'paused' : 'active',
            'next_fetch_at' => $nextFetch, 'lease_owner' => null, 'lease_until' => null, 'updated_at' => ebNow()]);
        ebAudit($username, $paused ? 'paused' : 'resumed', $accountId);
        return $nextFetch !== null;
    });
}

/** @return int Unlinked pending and review rows across every account belonging to a connection. */
function ebWaitingCountForConnection(int $connectionId): int
{
    return (int)ebRow('SELECT COUNT(*) AS n FROM bank_transactions t JOIN bank_accounts a ON a.id=t.account_id'
        . ' WHERE a.connection_id=' . (int)$connectionId . " AND t.review_state IN ('pending','review') AND t.link_id IS NULL")['n'];
}

/**
 * @param list<int> $txIds Visible transaction ids, at most 200.
 * @return array<int,string> Potential CSV/manual overlaps keyed by local transaction id.
 */
function ebOverlapHints(int $accountId, array $txIds): array
{
    $txIds = array_values(array_unique(array_map('intval', $txIds)));
    if (count($txIds) > 200) {
        throw new EbUserError(ebT(findtekst('bank.pick.max_200|Vælg højst 200 posteringer ad gangen.', ebLang())));
    }
    if ($txIds === []) {
        return [];
    }
    $account = ebRow('SELECT ledger_account FROM bank_accounts WHERE id=' . (int)$accountId);
    if (!$account || !$account['ledger_account']) {
        return [];
    }
    $ledger = (int)$account['ledger_account'];
    $out = [];
    foreach (ebRows('SELECT id, booking_date, date_override, amount FROM bank_transactions WHERE account_id=' . (int)$accountId . " AND review_state IN ('pending','review') AND id IN (" . implode(',', $txIds) . ')') as $tx) {
        // Same accounting date the import uses: a value date chosen with "Brug valørdato" wins over the booking date.
        $accountingDate = $tx['date_override'] ?: $tx['booking_date'];
        if (!$accountingDate) {
            continue;
        }
        $negative = str_starts_with($tx['amount'], '-');
        $amount = $negative ? substr($tx['amount'], 1) : $tx['amount'];
        $date = ebSql(substr((string)$accountingDate, 0, 10));
        $draft = ebRow('SELECT id FROM kassekladde WHERE transdate=' . $date . ' AND amount=' . ebSql($amount)
            . ' AND ' . ($negative ? 'kredit' : 'debet') . '=' . $ledger . ' AND ' . ($negative ? 'k_type' : 'd_type') . "='F' LIMIT 1");
        $posted = ebRow('SELECT id FROM transaktioner WHERE transdate=' . $date . ' AND kontonr=' . $ledger
            . ' AND ' . ($negative ? 'kredit' : 'debet') . '=' . ebSql($amount) . ' LIMIT 1');
        if ($draft || $posted) {
            $out[(int)$tx['id']] = ebT(findtekst('bank.feed.overlap_hint|Der findes allerede en linje med samme dato og beløb på bankkontoen. Kontrollér, om posteringen er bogført.', ebLang()));
        }
    }
    return $out;
}

/** @return list<array{id:string, status:string, environment:string, aspsp_name:string, aspsp_country:string, psu_type:string, valid_until:?string, created_at:string, last_error:?string}> Safe UI projection, excluding encrypted session material. */
function ebListConnections(): array
{
    return ebRows('SELECT id, status, environment, aspsp_name, aspsp_country, psu_type, valid_until, created_at, last_error FROM bank_connections ORDER BY id DESC');
}

/** @return list<array{id:string, connection_id:?string, display_name:string, display_identity:string, currency:string, cash_account_type:string, state:string, ledger_account:?string, import_from:?string, fetched_through:?string, last_fetch_at:?string, last_import_at:?string, next_fetch_at:?string, last_error_code:?string, last_error:?string}> */
function ebListAccounts(?int $connectionId = null): array
{
    return ebRows('SELECT id, connection_id, display_name, display_identity, currency, cash_account_type, state, ledger_account,
        import_from, fetched_through, last_fetch_at, last_import_at, next_fetch_at, last_error_code, last_error FROM bank_accounts'
        . ($connectionId === null ? '' : ' WHERE connection_id=' . (int)$connectionId) . ' ORDER BY id');
}

/** @return list<array{id:string, account_id:string, booking_date:?string, value_date:?string, date_override:?string, transaction_date:?string, amount:string, currency:string, description:string, counterparty:?string, bank_reference:?string, review_state:string, review_reason:?string, identical_group:string, changed_payload:?string, link_id:?string, bilag:?string, kladde_id:?string, kassekladde_id:?string, disposition:?string}> */
function ebListTransactions(int $accountId, string $reviewState, int $limit = 200, int $offset = 0, ?string $from = null, ?string $to = null): array
{
    if (!in_array($reviewState, ['pending', 'imported', 'review', 'ignored', 'pre_cutover'], true)) {
        throw new EbUserError(ebT(findtekst('bank.feed.unknown_filter|Ukendt filter.', ebLang())));
    }
    return ebRows('SELECT t.id, t.account_id, t.booking_date, t.value_date, t.date_override, t.transaction_date, t.amount, t.currency, t.description,
        t.counterparty, t.bank_reference, t.review_state, t.review_reason, t.identical_group, t.changed_payload, t.link_id,
        l.bilag, l.kladde_id, l.kassekladde_id, l.disposition FROM bank_transactions t LEFT JOIN bank_import_links l ON l.id=t.link_id
        WHERE t.account_id=' . (int)$accountId . ' AND t.review_state=' . ebSql($reviewState)
        . (ebDate($from) === null ? '' : ' AND COALESCE(t.date_override, t.booking_date)>=' . ebSql($from))
        . (ebDate($to) === null ? '' : ' AND COALESCE(t.date_override, t.booking_date)<=' . ebSql($to))
        . ' ORDER BY t.booking_date DESC, t.id DESC LIMIT ' . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset));
}

/** @return list<array{kontonr:string, beskrivelse:string}> Eligible financial chart accounts. */
function ebLedgerAccountOptions(int $regnaar): array
{
    return ebRows('SELECT kontonr, beskrivelse FROM kontoplan WHERE regnskabsaar=' . (int)$regnaar . " AND kontotype IN ('D','S') AND (lukket IS NULL OR lukket<>'on') ORDER BY kontonr");
}

/** @return array{pending:int, imported:int, review:int, ignored:int, pre_cutover:int} */
function ebCountsByState(int $accountId): array
{
    $out = ['pending' => 0, 'imported' => 0, 'review' => 0, 'ignored' => 0, 'pre_cutover' => 0];
    foreach (ebRows('SELECT review_state, COUNT(*) AS n FROM bank_transactions WHERE account_id=' . (int)$accountId . ' GROUP BY review_state') as $row) {
        $out[$row['review_state']] = (int)$row['n'];
    }
    return $out;
}

/**
 * @return array{
 *   state:string, connection_status:?string, last_error_code:?string, last_run_at:?string,
 *   valid_until:?string, days_left:?int, last_fetch_at:?string, last_import_at:?string,
 *   counts:array{pending:int, imported:int, review:int, ignored:int, pre_cutover:int},
 *   queued:int, last_run_status:?string, last_error:?string, next_fetch_at:?string,
 *   waiting:int, waiting_oldest:?string, Pending unlinked rows waiting to be picked and their oldest date.
 *   stale_hours:?int                    Whole hours since the last successful fetch; null when never fetched.
 * }
 */
function ebAccountStatus(int $accountId): array
{
    $account = ebRow('SELECT a.*, c.valid_until, c.status AS connection_status FROM bank_accounts a LEFT JOIN bank_connections c ON c.id=a.connection_id WHERE a.id=' . (int)$accountId);
    if (!$account) {
        throw new EbUserError(ebT(findtekst('bank.account.missing_here|Bankkontoen findes ikke i dette regnskab.', ebLang())));
    }
    $counts = ebCountsByState($accountId);
    $waiting = ebRow('SELECT COUNT(*) AS n, MIN(COALESCE(date_override, booking_date)) AS oldest'
        . ' FROM bank_transactions WHERE account_id=' . (int)$accountId . " AND review_state='pending' AND link_id IS NULL");
    $run = ebRow('SELECT status, started_at FROM bank_sync_runs WHERE account_id=' . (int)$accountId . ' ORDER BY started_at DESC, id DESC LIMIT 1');
    return ['state' => $account['state'], 'connection_status' => $account['connection_status'],
        'last_error_code' => $account['last_error_code'], 'last_run_at' => $run['started_at'] ?? null, 'valid_until' => $account['valid_until'],
        'days_left' => $account['valid_until'] === null ? null : max(0, (int)floor((strtotime($account['valid_until']) - time()) / 86400)),
        'last_fetch_at' => $account['last_fetch_at'], 'last_import_at' => $account['last_import_at'], 'counts' => $counts,
        'waiting' => (int)$waiting['n'], 'waiting_oldest' => $waiting['oldest'],
        'stale_hours' => $account['last_fetch_at'] === null ? null : max(0, (int)floor((time() - strtotime($account['last_fetch_at'])) / 3600)),
        'queued' => $counts['pending'], 'last_run_status' => $run['status'] ?? null, 'last_error' => $account['last_error'],
        'next_fetch_at' => $account['next_fetch_at']];
}
