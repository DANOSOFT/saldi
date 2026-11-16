<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebJournal.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261006 CDX/LH Created Enable Banking ebJournal backend service.
// 20261007 CDX/LH Retried delivery review items and drained queues across journals with a line cap.
// 20261007 CDX/LH Deferred simulated journals and validated base currency, precision and accounting date overrides.
// 20261007 CDX/LH Kept bank journals within one fiscal year and reused account destinations by year.
// 20261022 CDX/LH Verify bank journal ownership and open ledgers; allow delivery past abandoned staging.
// 20261028 CDX/LH Fence automatic delivery by date and validate manual selection atomically.
// 20261028 CL/LH A restored possible duplicate returns to review; restore says where the line went.
// 20261116 CL/LH Pull model: no bank-owned journals and no automatic delivery. ebImportIntoJournal() puts the lines a user picks into the journal the user works in; lines it cannot take stay under Nye with their reason.
// 20261116 CL/LH The user name is compared with kladdeliste.hvem as UTF-8 (ebUtf8()), also on an ISO-8859-15 tenant.

require_once __DIR__ . '/ebSchema.php';
require_once __DIR__ . '/ebStore.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/bilagNumber.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/journalSaveLock.php';

/**
 * @return array{id:int, start:string, end:string}|null The fiscal year the user works in ($regnaar), from grupper RA.
 */
function ebActiveFiscalYear(): ?array
{
    $regnaar = (int)($GLOBALS['regnaar'] ?? 0);
    $year = $regnaar > 0 ? ebRow("SELECT box1, box2, box3, box4 FROM grupper WHERE art='RA' AND kodenr=" . ebSql((string)$regnaar)) : null;
    if (!$year) {
        return null;
    }
    $start = sprintf('%04d-%02d-01', (int)$year['box2'], (int)$year['box1']);
    $endStart = sprintf('%04d-%02d-01', (int)$year['box4'], (int)$year['box3']);
    if (!ebDate($start) || !ebDate($endStart)) {
        return null;
    }
    return ['id' => $regnaar, 'start' => $start, 'end' => date('Y-m-t', strtotime($endStart))];
}

/**
 * Puts picked bank lines into an open journal, as unposted lines on the account's ledger account.
 *
 * The journal header is locked (and the journal's save lock taken, like a kassekladde save) for the whole
 * import. Nothing is written when the journal is missing, posted or simulated, or open by another user less
 * than an hour ago. Amounts, dates and texts come from bank_transactions, never from the request. A line whose
 * accounting date is outside the user's active fiscal year, in a closed VAT period, without an open ledger
 * account, or in a company whose base currency is not DKK stays pending with that reason; the rest are imported
 * with the journal's next voucher numbers. Lines that are no longer pending (already imported, skipped) are
 * reported as skipped, so a repeated request imports nothing.
 *
 * @param int $kladdeId kladdeliste.id the user works in.
 * @param int $accountId bank_accounts.id the lines belong to.
 * @param list<int|string> $ids Picked bank_transactions ids (1..200).
 * @param string $username Acting user, also the user whose own edit lock is accepted.
 * @return array{imported:int, skipped:list<array{id:int, reason:string}>}
 */
function ebImportIntoJournal(int $kladdeId, int $accountId, array $ids, string $username): array
{
    $ids = ebSelectedIds($ids);
    // kladdeliste.hvem comes back as UTF-8 (ebRows()); the session's user name may still be ISO-8859-15.
    $username = ebUtf8($username);
    $ownSaveLock = empty($GLOBALS['kkJournalLocked']);
    if ($ownSaveLock) {
        // A kassekladde save of the same journal stages and numbers its lines under this lock (MB-81).
        kkJournalLock($kladdeId);
    }
    try {
        return ebAtomic(function () use ($kladdeId, $accountId, $ids, $username): array {
            $journal = ebRow('SELECT id, bogfort, hvem, tidspkt FROM kladdeliste WHERE id=' . (int)$kladdeId . ' FOR UPDATE');
            if (!$journal) {
                throw new EbUserError(ebT(findtekst('bank.import.journal_missing|Kassekladden findes ikke. Intet er lagt ind.', ebLang())));
            }
            if ($journal['bogfort'] !== '-') {
                throw new EbUserError(ebT(findtekst('bank.import.journal_posted|Kassekladden er bogført eller simuleret. Intet er lagt ind.', ebLang())));
            }
            $holder = (string)$journal['hvem'];
            if ($holder !== '' && $holder !== $username) {
                $parts = preg_split('/\s+/', trim((string)$journal['tidspkt']));
                $seconds = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : time();
                if (time() - $seconds < 3600) {
                    throw new EbUserError(sprintf(ebT(findtekst('bank.import.journal_locked|Kassekladden er åben hos %s. Intet er lagt ind. Prøv igen, når kladden er lukket.', ebLang())), $holder));
                }
            }
            $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
            if (!$account || !ebCompanyEnabled() || !in_array($account['state'], ['active', 'paused', 'disconnected'], true)) {
                throw new EbUserError(ebT(findtekst('bank.import.account_unavailable|Bankkontoen kan ikke lægge posteringer i kassekladden.', ebLang())));
            }
            $rows = ebRows('SELECT *, COALESCE(date_override, booking_date) AS accounting_date FROM bank_transactions WHERE account_id='
                . (int)$accountId . ' AND id IN (' . implode(',', $ids) . ') ORDER BY COALESCE(date_override, booking_date), id FOR UPDATE');
            if (count($rows) !== count($ids)) {
                throw new EbUserError(ebT(findtekst('bank.import.foreign_ids|Posteringerne tilhører ikke bankkontoen. Intet er lagt ind.', ebLang())));
            }
            $year = ebActiveFiscalYear();
            $baseCurrency = ebBaseCurrency();
            $closedVat = ebTableExists('moms_periode_luk');
            $result = ['imported' => 0, 'skipped' => []];
            foreach ($rows as $tx) {
                $id = (int)$tx['id'];
                if ($tx['link_id'] !== null || !($tx['review_state'] === 'pending'
                    || ($tx['review_state'] === 'review' && $tx['review_reason'] === 'after_review'))) {
                    $result['skipped'][] = ['id' => $id, 'reason' => 'not_pending'];
                    continue;
                }
                $date = (string)$tx['accounting_date'];
                $reason = '';
                // The rules a fetched line was stored under still hold (amount with a third decimal, currency, date).
                $initial = ebInitialReview($tx, null);
                if ($initial['state'] === 'review') {
                    $reason = (string)$initial['reason'];
                } elseif ($baseCurrency !== 'DKK') {
                    $reason = 'base_currency';
                } elseif ($year === null || $date === '' || $date < $year['start'] || $date > $year['end']) {
                    $reason = 'outside_fiscal_year';
                } elseif ($closedVat && ebRow('SELECT 1 FROM moms_periode_luk WHERE kalender_aar=' . (int)substr($date, 0, 4)
                    . ' AND kalender_maaned=' . (int)substr($date, 5, 2) . " AND status='closed'")) {
                    $reason = 'closed_period';
                } elseif (!ebRow('SELECT kontonr FROM kontoplan WHERE kontonr=' . (int)$account['ledger_account'] . ' AND regnskabsaar=' . (int)$year['id']
                    . " AND kontotype IN ('D','S') AND (lukket IS NULL OR lukket<>'on')")) {
                    $reason = 'no_ledger_account';
                }
                if ($reason !== '') {
                    // The line stays under Nye; the reason is shown there until a later pick succeeds.
                    ebUpdate('bank_transactions', $id, ['review_state' => 'pending', 'review_reason' => $reason]);
                    $result['skipped'][] = ['id' => $id, 'reason' => $reason];
                    continue;
                }
                $bilag = bilagNextNumberForJournal($kladdeId, $year['start'], $year['end']);
                $pos = (int)ebRow('SELECT COALESCE(MAX(pos),0)+1 AS pos FROM kassekladde WHERE kladde_id=' . (int)$kladdeId)['pos'];
                $negative = str_starts_with($tx['amount'], '-');
                $amount = $negative ? substr($tx['amount'], 1) : $tx['amount'];
                $lineId = ebInsert('kassekladde', ['bilag' => $bilag, 'transdate' => $date,
                    'beskrivelse' => $tx['description'] ?: ebText(ebT(findtekst('bank.import.line_text|Bankpostering', ebLang())), 255),
                    'd_type' => 'F', 'debet' => $negative ? 0 : (int)$account['ledger_account'],
                    'k_type' => 'F', 'kredit' => $negative ? (int)$account['ledger_account'] : 0, 'faktura' => '', 'amount' => $amount,
                    'momsfri' => '', 'afd' => 0, 'projekt' => '', 'ansat' => '0', 'valuta' => 0, 'kladde_id' => $kladdeId, 'pos' => $pos]);
                $linkId = ebInsert('bank_import_links', ['transaction_id' => $id, 'account_id' => $accountId,
                    'kladde_id' => $kladdeId, 'kassekladde_id' => $lineId, 'bilag' => $bilag, 'disposition' => 'active',
                    'created_at' => ebNow(), 'created_by' => $username]);
                ebUpdate('bank_transactions', $id, ['review_state' => 'imported', 'review_reason' => null, 'link_id' => $linkId]);
                $result['imported']++;
            }
            if ($result['imported'] > 0) {
                ebUpdate('bank_accounts', $accountId, ['last_import_at' => ebNow(), 'updated_at' => ebNow()]);
            }
            ebAudit($username, 'import_selected', $accountId, null, 'Journal ' . (int)$kladdeId . '; selected: ' . count($ids)
                . '; imported: ' . $result['imported'] . '; skipped: ' . count($result['skipped']));
            return $result;
        });
    } finally {
        if ($ownSaveLock) {
            kkJournalUnlock();
        }
    }
}

/**
 * @param list<int|string> $ids Requested transaction ids; duplicate ids count once.
 * @return list<int> Validated unique positive ids, bounded to 1..200.
 */
function ebSelectedIds(array $ids): array
{
    foreach ($ids as $id) {
        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id <= 0) {
            throw new EbUserError(ebT(findtekst('bank.pick.invalid_ids|Vælg gyldige posteringer.', ebLang())));
        }
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (count($ids) < 1 || count($ids) > 200) {
        throw new EbUserError(ebT(findtekst('bank.pick.count_limit|Vælg mellem 1 og 200 posteringer ad gangen.', ebLang())));
    }
    sort($ids);
    return $ids;
}

/**
 * @param list<int|string> $ids Selected ids, validated together before any row is changed.
 * @return int Number of rows ignored in one transaction.
 */
function ebIgnoreSelected(int $accountId, array $ids, string $username): int
{
    $ids = ebSelectedIds($ids);
    return ebAtomic(function () use ($accountId, $ids, $username): int {
        if (!ebRow('SELECT id FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE')) {
            throw new EbUserError(ebT(findtekst('bank.account.missing|Bankkontoen findes ikke.', ebLang())));
        }
        $rows = ebRows('SELECT * FROM bank_transactions WHERE account_id=' . (int)$accountId
            . ' AND id IN (' . implode(',', $ids) . ') ORDER BY id FOR UPDATE');
        if (count($rows) !== count($ids)) {
            throw new EbUserError(ebT(findtekst('bank.pick.foreign_ids|Posteringerne tilhører ikke bankkontoen.', ebLang())));
        }
        foreach ($rows as $tx) {
            if ($tx['link_id'] !== null || !in_array($tx['review_state'], ['pending', 'review', 'pre_cutover'], true)) {
                throw new EbUserError(ebT(findtekst('bank.pick.cannot_skip|En af de valgte posteringer kan ikke springes over. Genindlæs siden.', ebLang())));
            }
        }
        ebWrite("UPDATE bank_transactions SET review_state='ignored' WHERE account_id=" . (int)$accountId
            . ' AND id IN (' . implode(',', $ids) . ')');
        ebAudit($username, 'ignored', $accountId, null, 'Ignored: ' . count($ids));
        return count($ids);
    });
}

/** @return array{deleted:int, posted:int} Preserve source identity when journal rows disappear or are posted. */
function ebReconcileLinks(int $accountId): array
{
    return ebAtomic(function () use ($accountId): array {
        ebRows('SELECT id FROM bank_accounts WHERE id=' . (int)$accountId . ' FOR UPDATE');
        $out = ['deleted' => 0, 'posted' => 0];
        $links = ebRows('SELECT * FROM bank_import_links WHERE account_id=' . (int)$accountId . " AND disposition='active' FOR UPDATE");
        foreach ($links as $link) {
            $line = ebRow('SELECT id FROM kassekladde WHERE id=' . (int)$link['kassekladde_id']);
            $journal = ebRow('SELECT bogfort FROM kladdeliste WHERE id=' . (int)$link['kladde_id']);
            $disposition = !$line ? 'deleted' : (($journal['bogfort'] ?? '') === 'V' ? 'posted' : '');
            if ($disposition !== '') {
                ebUpdate('bank_import_links', (int)$link['id'], ['disposition' => $disposition, 'disposition_at' => ebNow()]);
                $out[$disposition]++;
            }
        }
        return $out;
    });
}

/** @return array{ok:bool, message:string} Explicit feed action on server-stored accounting fields. */
function ebTransactionAction(int $txId, string $action, string $actor): array
{
    return ebAtomic(function () use ($txId, $action, $actor): array {
        $tx = ebRow('SELECT * FROM bank_transactions WHERE id=' . (int)$txId . ' FOR UPDATE');
        if (!$tx) {
            throw new EbUserError(ebT(findtekst('bank.tx.missing|Posteringen findes ikke i dette regnskab.', ebLang())));
        }
        $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . (int)$tx['account_id']);
        // A feed controller may bind the selected account explicitly, without changing the public signature.
        if (isset($GLOBALS['ebActionAccountId']) && (int)$GLOBALS['ebActionAccountId'] !== (int)$tx['account_id']) {
            throw new EbUserError(ebT(findtekst('bank.tx.foreign|Posteringen tilhører ikke bankkontoen.', ebLang())));
        }
        $fields = [];
        if ($action === 'ignore' && in_array($tx['review_state'], ['pending', 'review', 'pre_cutover'], true) && $tx['link_id'] === null) {
            $fields = ['review_state' => 'ignored'];
        } elseif ($action === 'restore' && $tx['review_state'] === 'ignored') {
            // Skipping keeps review_reason; a restored possible duplicate goes back to review, never to the automatic queue.
            $review = $tx['review_reason'] === 'possible_duplicate'
                ? ['state' => 'review', 'reason' => 'possible_duplicate'] : ebInitialReview($tx, $account['import_from']);
            $fields = ['review_state' => $review['state'], 'review_reason' => $review['reason']];
        } elseif ($action === 'reimport' && $tx['review_state'] === 'imported') {
            $link = ebRow('SELECT * FROM bank_import_links WHERE id=' . (int)$tx['link_id'] . ' FOR UPDATE');
            if ($link && $link['disposition'] === 'deleted') {
                ebUpdate('bank_import_links', (int)$link['id'], ['disposition' => 'replaced', 'disposition_at' => ebNow()]);
                $fields = ['review_state' => 'pending', 'review_reason' => null, 'link_id' => null];
            }
        } elseif ($action === 'import' && $tx['review_state'] === 'review' && in_array($tx['review_reason'], ['possible_duplicate', 'after_review'], true) && $tx['link_id'] === null) {
            $fields = ['review_state' => 'pending', 'review_reason' => null];
        } elseif ($action === 'import_value_date' && $tx['review_state'] === 'review' && $tx['review_reason'] === 'missing_date' && $tx['value_date'] !== null) {
            $tx['date_override'] = $tx['value_date'];
            $review = ebInitialReview($tx, $account['import_from']);
            $fields = ['date_override' => $tx['value_date'],
                'review_state' => $review['state'], 'review_reason' => $review['reason']];
        } elseif ($action === 'accept_change' && $tx['review_state'] === 'review' && $tx['review_reason'] === 'changed_after_import') {
            $fields = ['review_state' => 'imported', 'review_reason' => null];
            // Retain the original accounting values; remember the acknowledged payload hash to avoid reflagging it.
            $changed = json_decode($tx['changed_payload'] ?? '', true);
            if (is_array($changed)) {
                $fields['payload_hash'] = ebPayloadHash($changed);
            }
        }
        if ($fields === []) {
            throw new EbUserError(ebT(findtekst('bank.tx.not_allowed|Handlingen er ikke tilladt for denne postering.', ebLang())));
        }
        ebUpdate('bank_transactions', $txId, $fields);
        ebAudit($actor, ['ignore' => 'ignored', 'restore' => 'restored', 'reimport' => 'reimport'][$action] ?? 'import_review', (int)$tx['account_id'], null, $action);
        $state = $action === 'accept_change' ? '' : $fields['review_state'];
        $messages = [
            'pending' => findtekst('bank.tx.now_pending|Posteringen ligger nu under Nye, hvor du kan lægge den i kassekladden.', ebLang()),
            'review' => findtekst('bank.tx.now_review|Posteringen ligger nu under Til gennemsyn.', ebLang()),
            'pre_cutover' => findtekst('bank.tx.now_pre_cutover|Posteringen ligger nu under Før startdato.', ebLang()),
            'ignored' => findtekst('bank.tx.now_ignored|Posteringen er sprunget over. Den kan gendannes under Sprunget over.', ebLang()),
        ];
        return ['ok' => true, 'message' => ebT($messages[$state] ?? findtekst('bank.tx.updated|Posteringen er opdateret.', ebLang()))];
    });
}
