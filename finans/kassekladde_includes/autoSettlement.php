<?php
// finans/kassekladde_includes/autoSettlement.php --- 2026-09-22
// Copyright (c) 2026 Danosoft ApS
// 20260908 CDX/LH Scope automatic settlement to the chosen account and validate live rows before saving.
// 20260922 CDX/PHR Allow unfiltered suggestions without broadening malformed account filters.
// 20260922 CL/NTR Add autoSettlementCandidateSignalWhere() so the unfiltered open_post search
//                 can bound its SQL fetch without dropping any row the ranking could select.

/**
 * Identify the customer/supplier side, or the empty side of an imported bank line.
 *
 * @return array{account: string, accountType: string, field: string, amount: float}|null
 */
function autoSettlementContext(array $entry) {
    $accounts = [];
    foreach (['debet' => 'd_type', 'kredit' => 'k_type'] as $field => $typeField) {
        $account = trim((string)($entry[$field] ?? ''));
        $type = trim((string)($entry[$typeField] ?? ''));
        if ($account && in_array($type, ['D', 'K'], true)) {
            $accounts[] = ['account' => $account, 'accountType' => $type, 'field' => $field];
        }
    }
    if (count($accounts) > 1 || (float)($entry['amount'] ?? 0) == 0) return null;
    if ($accounts) {
        if (trim((string)($entry['faktura'] ?? '')) !== '') return null;
        $context = $accounts[0];
    } else {
        $debit = (bool)trim((string)($entry['debet'] ?? ''));
        $credit = (bool)trim((string)($entry['kredit'] ?? ''));
        if ($debit === $credit) return null;
        $context = ['account' => '', 'accountType' => '', 'field' => $debit ? 'kredit' : 'debet'];
    }
    $context['amount'] = (float)$entry['amount'] * ($context['field'] === 'debet' ? -1 : 1);
    return $context;
}

/** @return string Fingerprint of the journal values the user reviewed. */
function autoSettlementSnapshot(array $entry) {
    $values = [];
    foreach (['id', 'kladde_id', 'debet', 'kredit', 'd_type', 'k_type', 'faktura', 'amount', 'transdate', 'valuta', 'valutakurs', 'beskrivelse'] as $field) {
        $values[$field] = (string)($entry[$field] ?? '');
    }
    return hash('sha256', json_encode($values));
}

/** @return string SQL predicate that never broadens an incomplete account filter. */
function autoSettlementAccountWhere($account, $type) {
    if (!is_string($account) || !is_string($type)) return '1 = 0';
    $account = trim($account);
    $type = trim($type);
    if ($account === '' || !in_array($type, ['D', 'K'], true)) return '1 = 0';
    $account = db_escape_string($account);
    return "openpost.konto_nr = '$account' AND adresser.kontonr = '$account' AND adresser.art = '$type'";
}

/** @return string SQL predicate for an optional customer/supplier search filter. */
function autoSettlementSearchWhere($account, $type) {
    if (!is_string($account) || !is_string($type)) return '1 = 0';
    $account = trim($account);
    $type = strtoupper(trim($type));
    if ($account === '' && $type === '') {
        return "adresser.art IN ('D', 'K') AND openpost.konto_nr = adresser.kontonr";
    }
    return autoSettlementAccountWhere($account, $type);
}

/**
 * SQL predicate matching every open post that could score above zero in the PHP/JS ranking
 * (autoSettlementAmountMatches, plus hint-token/description-word matches on invoice number,
 * account number and company name). It over-matches rather than under-matches: PHP re-applies
 * the exact rules afterwards, but a row excluded here would never reach that scoring pass,
 * so this must stay a superset of every rule it mirrors.
 *
 * @param float|null $amount Exact amount to match, or null to skip the amount signal.
 * @param array $hintTokens Uppercased-in-JS tokens checked against invoice/account numbers.
 * @param array $descWords Uppercased-in-JS words checked against invoice numbers and company names.
 * @return string
 */
function autoSettlementCandidateSignalWhere($amount, array $hintTokens, array $descWords) {
    $conds = [];

    if ($amount !== null) {
        $amountLiteral = db_escape_string((string)(float)$amount);
        $conds[] = "ABS(ABS(openpost.amount) - ABS($amountLiteral)) < 0.001";
    }

    $tokens = [];
    foreach (array_merge($hintTokens, $descWords) as $tok) {
        $tok = trim((string)$tok);
        if ($tok !== '') $tokens[] = $tok;
    }
    $tokens = array_unique($tokens);

    foreach ($tokens as $tok) {
        // Escape ILIKE wildcards (%, _) and the escape character itself so a token containing
        // them is matched as a literal substring, not a pattern.
        $likeSafe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tok);
        $escaped = db_escape_string($likeSafe);
        $conds[] = "CAST(openpost.faktnr AS TEXT) ILIKE '%$escaped%' ESCAPE '\\'";
        $conds[] = "CAST(openpost.konto_nr AS TEXT) ILIKE '%$escaped%' ESCAPE '\\'";
        $conds[] = "adresser.firmanavn ILIKE '%$escaped%' ESCAPE '\\'";
    }

    return $conds ? implode(' OR ', $conds) : '1 = 0';
}

/** @return bool Whether an automatic match has the same amount and payment direction. */
function autoSettlementAmountMatches($amount, $currentAmount) {
    return $currentAmount !== null && abs((float)$amount - (float)$currentAmount) < 0.001;
}

/**
 * Decide before pagination or client-side removal of already-used invoices.
 *
 * @param array $sortedRows All matching open posts, sorted by score descending.
 * @return int|null The unique highest-scored exact match, or null when selection must be manual.
 */
function autoSettlementBestCandidateId(array $sortedRows) {
    if (!$sortedRows || !$sortedRows[0]['amountMatch']) return null;
    if (isset($sortedRows[1]) && $sortedRows[0]['_score'] <= $sortedRows[1]['_score']) return null;
    return (int)$sortedRows[0]['id'];
}

/** @return void */
function autoSettlementWrite($sql) {
    $result = db_modify($sql, __FILE__ . ' line ' . __LINE__);
    if (!is_string($result) || strncmp($result, "0\t", 2) !== 0) {
        throw new RuntimeException('The journal could not be saved. Reload and try again.');
    }
}

/**
 * Resolve invoice/account values from the selected open post, never from submitted labels.
 * Partial payments remain valid: selecting an invoice does not change the journal amount.
 *
 * @return void
 * @throws RuntimeException When the journal, line, account or open post is no longer eligible.
 */
function saveAutoSettlement($journalId, $entryId, $openpostId, $accountId, $snapshot) {
    $journalId = is_scalar($journalId) ? filter_var($journalId, FILTER_VALIDATE_INT) : false;
    $entryId = is_scalar($entryId) ? filter_var($entryId, FILTER_VALIDATE_INT) : false;
    $openpostId = is_scalar($openpostId) ? filter_var($openpostId, FILTER_VALIDATE_INT) : false;
    $accountId = is_scalar($accountId) ? filter_var($accountId, FILTER_VALIDATE_INT) : false;
    if ($journalId <= 0 || $entryId <= 0 || $openpostId <= 0 || $accountId <= 0 || !is_string($snapshot)) {
        throw new RuntimeException('Choose an account and an open entry before settling.');
    }
    autoSettlementWrite('BEGIN');
    try {
        $journal = db_fetch_array(db_select("SELECT bogfort FROM kladdeliste WHERE id = $journalId FOR UPDATE", __FILE__ . ' line ' . __LINE__));
        if (!$journal || !in_array(trim((string)$journal['bogfort']), ['-', '!'], true)) {
            throw new RuntimeException('This journal is no longer open. Reload the journal.');
        }
        $entry = db_fetch_array(db_select("SELECT * FROM kassekladde WHERE id = $entryId AND kladde_id = $journalId FOR UPDATE", __FILE__ . ' line ' . __LINE__));
        if (!$entry || !hash_equals(autoSettlementSnapshot($entry), $snapshot)) {
            throw new RuntimeException('This journal line has changed. Reload before settling.');
        }
        $context = autoSettlementContext($entry);
        if (!$context) throw new RuntimeException('This journal line can no longer be settled here.');
        $post = db_fetch_array(db_select("SELECT * FROM openpost WHERE id = $openpostId FOR UPDATE", __FILE__ . ' line ' . __LINE__));
        $account = db_fetch_array(db_select("SELECT kontonr, art FROM adresser WHERE id = $accountId FOR UPDATE", __FILE__ . ' line ' . __LINE__));
        if (!$post || !$account || (int)$post['konto_id'] !== $accountId
            || (string)$post['udlignet'] === '1'
            || trim((string)$post['konto_nr']) !== trim((string)$account['kontonr'])
            || !in_array(trim((string)$account['art']), ['D', 'K'], true)) {
            throw new RuntimeException('The selected open entry is no longer available for this account. Search again.');
        }
        if (trim((string)$post['faktnr']) === '') {
            throw new RuntimeException('The selected open entry has no invoice reference to assign.');
        }
        if ($context['account'] !== '' && ($context['account'] !== trim((string)$account['kontonr'])
            || $context['accountType'] !== trim((string)$account['art']))) {
            throw new RuntimeException('The selected entry belongs to a different account than the journal line.');
        }
        $invoice = db_escape_string(trim((string)$post['faktnr']));
        $assignments = "faktura = '$invoice'";
        if ($context['account'] === '') {
            $number = db_escape_string(trim((string)$account['kontonr']));
            $type = db_escape_string(trim((string)$account['art']));
            $field = $context['field'];
            $typeField = $field === 'debet' ? 'd_type' : 'k_type';
            $assignments .= ", $field = '$number', $typeField = '$type'";
        }
        autoSettlementWrite("UPDATE kassekladde SET $assignments WHERE id = $entryId AND kladde_id = $journalId");
        autoSettlementWrite('COMMIT');
    } catch (Throwable $error) {
        autoSettlementWrite('ROLLBACK');
        throw $error;
    }
}
